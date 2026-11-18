<?php if (!empty($error)) { ?>
    <?php echo $error ?>
<?php } else { ?>

    <ul class="list-unstyled">
        <?php foreach ($content as $row) { ?>
            <li>
                <a class="title" href="<?php echo $row['theme_link'] ?>"
                   ><?php echo characterLimiter($row['title'], config('forum_threads.characters_limit')) ?></a>
                <span><?php echo Yii::t('main', 'forumThreads.author') ?></span> <a href="<?php echo $row['user_link'] ?>"
                                       ><?php echo e($row['starter_name']) ?></a>
                <span class="date"><span><?php echo Yii::t('cabinet', 'referrals.date') ?> </span><?php echo $row['start_date'] ?></span><br>
            </li>
        <?php } ?>
    </ul>

<?php } ?>