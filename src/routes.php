<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->post('/login', function (Request $request, Response $response, array $args) use ($container) {
        $member_id = $request->getParsedBodyParam("member");
        $pass = $request->getParsedBodyParam("pass");
        if (auth($this->db, $member_id, $pass)) {
            $token = makeToken();
            saveToken($this->db, $token, $member_id);
            return $response->withRedirect("/");
        } else {
            $args['members'] = getMembers($this->db);
            $args['member_id'] = $member_id;
            $args['error'] = 'ログインに失敗しました';
            return $container->get('renderer')->render($response, 'login.phtml', $args);
        }
    });

    $app->post('/password', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        $args['me'] = $me;
        if (empty($request->getParsedBodyParam("pass"))) {
            $args['error'] = "パスワードが入力されていません";
            return $container->get('renderer')->render($response, 'password.phtml', $args);
        }
        updatePassword($this->db, $me['id'], $request->getParsedBodyParam("pass"));
        return $response->withRedirect("/");
    });

    $app->post('/schedule', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        $args['me'] = $me;
        updateAnswers($this->db, $me['id'], $request->getParsedBodyParam("date"), $request->getParsedBodyParam("schedule_type_id"), $request->getParsedBodyParam("comment"));

        return $response->withRedirect("/");
    });

    $app->post('/member', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $member_id = $request->getParsedBodyParam("member_id");
        $name = $request->getParsedBodyParam("name");
        $is_admin = $request->getParsedBodyParam("is_admin");
        $remove = $request->getParsedBodyParam("remove");

        if (isExistsAdmin($is_admin, $remove)) {
            if (updateMembers($this->db, $member_id, $name, $is_admin, $remove)) {
                $args['info'] = '更新しました';
            } else {
                $args['error'] = '更新に失敗しました';
            }
        } else {
            $args['error'] = '最低ひとりは管理者にしてください';
        }

        $args['members'] = getMembers($this->db);

        return $container->get('renderer')->render($response, 'member.phtml', $args);
    });

    $app->post('/chpassword', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        if (empty($request->getParsedBodyParam("member"))) {
            $args['error'] = "利用者が選択されていません";
            return $container->get('renderer')->render($response, 'chpassword.phtml', $args);
        }
        if (empty($request->getParsedBodyParam("pass"))) {
            $args['error'] = "パスワードが入力されていません";
            return $container->get('renderer')->render($response, 'chpassword.phtml', $args);
        }
        updatePassword($this->db, $request->getParsedBodyParam("member"), $request->getParsedBodyParam("pass"));
        $args['members'] = getMembers($this->db);
        $args['info'] = "パスワードを更新しました";
        return $container->get('renderer')->render($response, 'chpassword.phtml', $args);
    });

    $app->post('/date', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        updateDates($this->db, $request->getParsedBodyParam("date"), $request->getParsedBodyParam("comment"));
        $args['scheduleDates'] = getScheduleDates($this->db);

        return $container->get('renderer')->render($response, 'date.phtml', $args);
    });

    $app->post('/symbol', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $schedule_type_id = $request->getParsedBodyParam("schedule_type_id");
        $symbol = $request->getParsedBodyParam("symbol");
        $desc = $request->getParsedBodyParam("desc");
        $remove = $request->getParsedBodyParam("remove");
        if (updateScheduleTypes($this->db, $schedule_type_id, $symbol, $desc, $remove)) {
            $args['info'] = '更新しました';
        } else {
            $args['error'] = '更新に失敗しました';
        }
        $args['scheduleTypes'] = getScheduleTypes($this->db);

        return $container->get('renderer')->render($response, 'symbol.phtml', $args);
    });



    $app->get('/logout', function (Request $request, Response $response) use ($container) {
        removeToken($this->db);
        return $response->withRedirect("/");
    });

    $app->get('/login', function (Request $request, Response $response, array $args) use ($container) {
        $args['members'] = getMembers($this->db);
        return $container->get('renderer')->render($response, 'login.phtml', $args);
    });

    $app->get('/manual', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        $args['me'] = $me;
        $args['scheduleTypes'] = getscheduleTypes($this->db);
        return $container->get('renderer')->render($response, 'manual.phtml', $args);
    });

    $app->get('/password', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        $args['me'] = $me;
        return $container->get('renderer')->render($response, 'password.phtml', $args);
    });

    $app->get('/schedule', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        $args['me'] = $me;
        $args['schedules'] = getSelfSchedules($this->db);
        $args['scheduleDates'] = getScheduleDates($this->db);
        $args['scheduleTypes'] = getscheduleTypes($this->db);

        return $container->get('renderer')->render($response, 'schedule.phtml', $args);
    });

    $app->get('/member', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $args['members'] = getMembers($this->db);

        return $container->get('renderer')->render($response, 'member.phtml', $args);
    });

    $app->get('/chpassword', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $args['members'] = getMembers($this->db);

        return $container->get('renderer')->render($response, 'chpassword.phtml', $args);
    });

    $app->get('/date', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $args['scheduleDates'] = getScheduleDates($this->db);

        return $container->get('renderer')->render($response, 'date.phtml', $args);
    });

    $app->get('/symbol', function (Request $request, Response $response, array $args) use ($container) {
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        if (!$me['is_admin']) {
            return $response->withRedirect("/");
        }
        $args['me'] = $me;
        $args['scheduleTypes'] = getScheduleTypes($this->db);

        return $container->get('renderer')->render($response, 'symbol.phtml', $args);
    });

    $app->get('/[{name}]', function (Request $request, Response $response, array $args) use ($container) {
        // Sample log message
        //$container->get('logger')->info("littleking '/' route");
        if (!$me = getSelfMember($this->db)) {
            return $response->withRedirect("/login");
        }
        updateScheduleTerm($this->db);
        $args['me'] = $me;
        $args['members'] = getMembers($this->db);
        $args['scheduleDates'] = getScheduleDates($this->db);
        $args['scheduleTypes'] = getscheduleTypes($this->db);
        $args['answers'] = getAnswers($this->db);
        $args['sammary'] = scheduleSummary($args['members'], $args['scheduleDates'], $args['answers']);

        return $container->get('renderer')->render($response, 'list.phtml', $args);
    });
};
