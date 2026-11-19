package update

import (
	"context"
	"crypto/x509"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"strconv"
	"time"
)

const downloadChunk = int64(8 << 20)
const reconnectLimit = 16

type responseError struct {
	code  int
	after time.Duration
}

func (e *responseError) Error() string {
	return fmt.Sprintf("сервер загрузки: HTTP %d", e.code)
}
func serverError(r *http.Response) error {
	var after time.Duration
	if seconds, e := strconv.Atoi(r.Header.Get("Retry-After")); e == nil && seconds > 0 {
		after = time.Duration(seconds) * time.Second
	} else if date, e := http.ParseTime(r.Header.Get("Retry-After")); e == nil {
		after = time.Until(date)
	}
	if after > 2*time.Minute {
		after = 2 * time.Minute
	}
	return &responseError{r.StatusCode, after}
}
func transient(err error) bool {
	var status *responseError
	if errors.As(err, &status) {
		switch status.code {
		case 408, 425, 429, 500, 502, 503, 504:
			return true
		}
		return false
	}
	var unknown x509.UnknownAuthorityError
	var invalid x509.CertificateInvalidError
	var hostname x509.HostnameError
	if errors.As(err, &unknown) || errors.As(err, &invalid) || errors.As(err, &hostname) {
		return false
	}
	var timed net.Error
	if errors.As(err, &timed) && (timed.Timeout() || timed.Temporary()) {
		return true
	}
	var op *net.OpError
	return errors.As(err, &op) || errors.Is(err, io.ErrUnexpectedEOF) || errors.Is(err, io.EOF) || errors.Is(err, context.DeadlineExceeded)
}
func (e *Engine) reconnect(ctx context.Context, path string, attempt int, cause error) error {
	if ctx.Err() != nil {
		return ctx.Err()
	}
	if !transient(cause) {
		return cause
	}
	if attempt > reconnectLimit {
		return fmt.Errorf("соединение долго недоступно; загрузка сохранена и может быть продолжена: %w", cause)
	}
	delay := 500 * time.Millisecond
	for n := 1; n < attempt && delay < 30*time.Second; n++ {
		delay *= 2
	}
	if delay > 30*time.Second {
		delay = 30 * time.Second
	}
	var status *responseError
	if errors.As(cause, &status) && status.after > delay {
		delay = status.after
	}
	if e.retryDelay != nil {
		delay = e.retryDelay(attempt)
	}
	e.emit(Event{Phase: "Восстановление соединения", File: fmt.Sprintf("%s · переподключение %d", path, attempt), Retrying: true})
	timer := time.NewTimer(delay)
	defer timer.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-timer.C:
		return nil
	}
}
