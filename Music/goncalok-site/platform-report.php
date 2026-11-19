<?php
// ============================================================
// GONCA LÖK — PDF rapor içerikleri (Faz 5)
// ============================================================
require_once __DIR__ . '/platform-db.php';
require_once __DIR__ . '/platform-pdf.php';

function platform_report_period(): array
{
    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));
    $valid = function ($v) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
    };
    $from = $valid($from) ? $from : '';
    $to = $valid($to) ? $to : '';
    if ($from !== '' && $to !== '' && strtotime($from) > strtotime($to)) {
        [$from, $to] = [$to, $from];
    }
    return [$from, $to];
}

function platform_report_period_params(): array
{
    [$from, $to] = platform_report_period();
    $params = [];
    if ($from !== '') { $params['from'] = $from; }
    if ($to !== '') { $params['to'] = $to; }
    return $params;
}

function platform_report_in_window(?string $date, string $from, string $to): bool
{
    $d = substr((string) $date, 0, 10);
    if ($d === '') { return $from === '' && $to === ''; }
    return ($from === '' || $d >= $from) && ($to === '' || $d <= $to);
}

function platform_report_period_label(string $from, string $to): string
{
    if ($from === '' && $to === '') { return 'Tüm kayıtlar'; }
    return ($from ?: 'Başlangıç yok') . ' — ' . ($to ?: 'Bitiş yok');
}

function platform_report_header(PlatformPdf $pdf, string $title, string $subtitle): float
{
    $pdf->setFont(19, true);
    $pdf->text(48, 58, 'Gonca LÖK');
    $pdf->setFont(10);
    $pdf->textRight(547, 58, 'Koçluk Takip Platformu');
    $pdf->line(48, 68, 547, 68, 1.2);
    $pdf->setFont(16, true);
    $pdf->text(48, 96, $title);
    $pdf->setFont(9);
    $pdf->text(48, 112, $subtitle);
    $pdf->line(48, 120, 547, 120, 0.5);
    return 138.0;
}

function platform_write_student_report(PlatformPdf $pdf, int $studentId, string $scope, string $from, string $to): string
{
    $student = platform_get_student($studentId);
    if (!$student) { throw new RuntimeException('Öğrenci bulunamadı.'); }
    $shared = $scope === 'shared';
    $fullName = trim($student['first_name'] . ' ' . $student['last_name']);

    $y = platform_report_header(
        $pdf,
        $shared ? 'Öğrenci Gelişim Raporu' : 'Öğrenci Koçluk Dosyası Raporu',
        'Dönem: ' . platform_report_period_label($from, $to) . '   |   Oluşturma: ' . date('d.m.Y H:i')
    );

    $pdf->setFont(9, true);
    $pdf->text(48, $y, $fullName . '  ·  ' . $student['student_code']);
    $pdf->textRight(547, $y, $shared ? 'PAYLAŞILAN SÜRÜM' : 'KOÇ SÜRÜMÜ');
    $y += 18;
    $pdf->row('Program', (string) $student['coaching_type'], $y, 110);
    $pdf->row('Durum', platform_student_statuses()[$student['status']] ?? $student['status'], $y, 110);
    if ($student['school'] || $student['grade_level']) {
        $pdf->row('Okul / Sınıf', trim((string) $student['school'] . ' ' . (string) $student['grade_level']), $y, 110);
    }
    if (!$shared) {
        $pdf->row('Risk Seviyesi', platform_risk_levels()[$student['risk_level']] ?? $student['risk_level'], $y, 110);
        if ($student['email']) { $pdf->row('E-posta', $student['email'], $y, 110); }
        if ($student['phone']) { $pdf->row('Telefon', $student['phone'], $y, 110); }
        if ($student['guardian_name']) {
            $pdf->row('Veli', trim((string) $student['guardian_name'] . ' ' . (string) $student['guardian_phone'] . ' ' . (string) $student['guardian_email']), $y, 110);
        }
    }
    $y += 8;

    $sessions = array_values(array_filter(platform_get_sessions($studentId), function ($s) use ($from, $to) {
        return platform_report_in_window($s['session_date'], $from, $to);
    }));
    $goals = platform_get_goals($studentId);
    $actions = platform_get_actions($studentId);
    $subjects = platform_get_subjects($studentId);
    $lessons = array_values(array_filter(platform_get_lessons($studentId), function ($l) use ($from, $to) {
        return platform_report_in_window($l['lesson_date'], $from, $to);
    }));
    $exams = array_values(array_filter(platform_get_exams($studentId), function ($e) use ($from, $to) {
        return platform_report_in_window($e['exam_date'], $from, $to);
    }));
    $career = platform_get_career_profile($studentId);
    $applications = platform_get_applications($studentId);
    $flags = $shared ? [] : platform_get_risk_flags($studentId, 'open');
    $interventions = $shared ? [] : platform_get_interventions($studentId);

    $openActions = count(array_filter($actions, function ($a) { return $a['status'] !== 'done'; }));
    $activeGoals = count(array_filter($goals, function ($g) { return $g['status'] === 'active'; }));

    $pdf->heading('Genel Görünüm', $y);
    $pdf->row('Seans kaydı', (string) count($sessions), $y, 150);
    $pdf->row('Aktif hedef / açık ödev', $activeGoals . ' / ' . $openActions, $y, 150);
    $pdf->row('Çalışma / sınav kaydı', count($lessons) . ' / ' . count($exams), $y, 150);
    if (!$shared) { $pdf->row('Açık risk uyarısı', (string) count($flags), $y, 150); }
    $y += 8;

    $pdf->heading('Seanslar', $y);
    if (!$sessions) {
        $pdf->paragraph(56, $y, 'Bu dönemde seans kaydı bulunmuyor.', 480, 13, 9);
        $y += 18;
    }
    foreach ($sessions as $s) {
        $pdf->setFont(9, true);
        $pdf->ensureSpace($y, 20);
        $pdf->text(56, $y, substr((string) $s['session_date'], 0, 10) . ' ' . substr((string) $s['session_time'], 0, 5)
            . ' — ' . ($s['template_name'] ?: $s['session_type'] ?: 'Seans'));
        $y += 14;
        if ($s['shared_note']) {
            $pdf->setFont(9);
            $y = $pdf->paragraph(66, $y, 'Paylaşılan özet: ' . $s['shared_note'], 470, 12, 9);
        }
        if (!$shared && $s['private_note']) {
            $pdf->setFont(9);
            $y = $pdf->paragraph(66, $y, 'Özel koç notu: ' . $s['private_note'], 470, 12, 9);
        }
        if (!$shared && ($s['mood_before'] || $s['mood_after'])) {
            $pdf->setFont(8);
            $pdf->text(66, $y, 'Ruh hali: ' . ($s['mood_before'] ?: '-') . ' -> ' . ($s['mood_after'] ?: '-'));
            $y += 12;
        }
        $y += 6;
        $pdf->line(56, $y, 547, $y, 0.3);
        $y += 8;
    }

    $pdf->heading('Hedefler ve Ödevler', $y);
    if (!$goals && !$actions) {
        $pdf->paragraph(56, $y, 'Hedef veya ödev kaydı bulunmuyor.', 480, 13, 9);
        $y += 18;
    }
    foreach ($goals as $g) {
        $pdf->setFont(9, true);
        $pdf->text(56, $y, 'Hedef: ' . $g['title'] . ' (%' . (int) $g['progress'] . ')');
        $y += 13;
        if ($g['description']) { $y = $pdf->paragraph(66, $y, $g['description'], 470, 12, 9); }
        $y += 4;
    }
    foreach ($actions as $a) {
        $pdf->setFont(9);
        $status = ['open' => 'Açık', 'in_progress' => 'Devam', 'done' => 'Tamamlandı', 'overdue' => 'Gecikti'][$a['status']] ?? $a['status'];
        $pdf->bullet($a['title'] . ' — ' . $status . ($a['due_date'] ? ' (Termin: ' . substr((string) $a['due_date'], 0, 10) . ')' : ''), $y);
    }
    $y += 6;

    $pdf->heading('Akademik Takip', $y);
    foreach ($subjects as $s) {
        $pdf->setFont(9);
        $pdf->bullet($s['name'] . ' — güncel: ' . ($s['current_grade'] ?: '—') . ' / hedef: ' . ($s['target_grade'] ?: '—'), $y);
    }
    foreach ($lessons as $l) {
        $pdf->setFont(9);
        $pdf->bullet(substr((string) $l['lesson_date'], 0, 10) . ' · ' . $l['topic'] . ' (' . (int) $l['duration_min'] . ' dk)', $y);
        if ($l['homework']) { $y = $pdf->paragraph(74, $y, 'Çalışma: ' . $l['homework'], 460, 12, 8); }
        if (!$shared && $l['coach_note']) { $y = $pdf->paragraph(74, $y, 'Koç notu: ' . $l['coach_note'], 460, 12, 8); }
    }
    foreach ($exams as $e) {
        $pdf->setFont(9);
        $pdf->bullet(
            substr((string) $e['exam_date'], 0, 10) . ' · ' . $e['exam_name'] . ' — '
            . ($e['score'] ?? '—') . '/' . ($e['max_score'] ?? 100)
            . ' (hedef: ' . ($e['target_score'] ?? '—') . ')',
            $y
        );
        if (!$shared && $e['note']) { $y = $pdf->paragraph(74, $y, 'Not: ' . $e['note'], 460, 12, 8); }
    }
    if (!$subjects && !$lessons && !$exams) {
        $pdf->paragraph(56, $y, 'Akademik kayıt bulunmuyor.', 480, 13, 9);
        $y += 18;
    }

    if (!$shared) {
        $pdf->heading('Kariyer', $y);
        $pdf->row('Hedef üniversite', (string) ($career['target_university'] ?? ''), $y, 130);
        $pdf->row('Hedef bölüm', (string) ($career['target_department'] ?? ''), $y, 130);
        $pdf->row('Hedef rol', (string) ($career['target_role'] ?? ''), $y, 130);
        foreach ($applications as $a) {
            $pdf->setFont(9);
            $pdf->bullet($a['organization'] . ' — ' . $a['status'] . ($a['deadline'] ? ' (Son tarih: ' . $a['deadline'] . ')' : ''), $y);
        }
        $pdf->heading('Risk ve Müdahale', $y);
        if (!$flags && !$interventions) {
            $pdf->paragraph(56, $y, 'Açık risk uyarısı veya müdahale kaydı yok.', 480, 13, 9);
            $y += 18;
        }
        foreach ($flags as $f) {
            $pdf->bullet('[' . $f['severity'] . '] ' . $f['message'], $y);
        }
        foreach ($interventions as $i) {
            $pdf->bullet($i['action'] . ' — ' . ($i['status'] === 'done' ? 'Tamamlandı' : 'Açık'), $y);
        }
    } else {
        $pdf->heading('Kariyer Hedefleri', $y);
        if ($career['target_university'] || $career['target_department'] || $career['target_role']) {
            $pdf->row('Hedef', trim((string) ($career['target_university'] ?? '') . ' ' . (string) ($career['target_department'] ?? '') . ' ' . (string) ($career['target_role'] ?? '')), $y, 90);
        }
        foreach ($applications as $a) {
            $status = ['preparing' => 'Hazırlanıyor', 'submitted' => 'Gönderildi', 'interview' => 'Görüşme', 'accepted' => 'Kabul', 'rejected' => 'Reddedildi'][$a['status']] ?? $a['status'];
            $pdf->bullet($a['organization'] . ' — ' . $status, $y);
        }
    }

    $pdf->setFont(8);
    $pdf->line(48, 790, 547, 790, 0.4);
    $pdf->text(48, 804, $shared
        ? 'Bu rapor yalnızca öğrenci/veli ile paylaşım içindir; özel koç notlarını içermez.'
        : 'Bu rapor koçluk dosyası kapsamında hazırlanmıştır. Gizli bilgi içerir.');

    platform_db_audit('report_generated', 'student', $studentId, ($shared ? 'Paylaşılan' : 'Koç') . ' PDF raporu, dönem: ' . platform_report_period_label($from, $to));

    return preg_replace('/[^a-zA-Z0-9_-]/', '_', 'ogrenci-' . $student['student_code'] . '-' . ($shared ? 'paylasilan' : 'koc')) . '.pdf';
}

function platform_write_overview_report(PlatformPdf $pdf): string
{
    [$from, $to] = platform_report_period();
    $students = platform_get_students();
    $openFlags = platform_get_open_risk_count();
    $stats = platform_get_overview();

    $y = platform_report_header(
        $pdf,
        'Koçluk Programı Genel Raporu',
        'Dönem: ' . platform_report_period_label($from, $to) . '   |   Oluşturma: ' . date('d.m.Y H:i')
    );
    $pdf->heading('Özet', $y);
    $pdf->row('Aktif öğrenci', (string) $stats['active_students'], $y, 170);
    $pdf->row('Risk altındaki öğrenci', (string) $stats['risk_students'], $y, 170);
    $pdf->row('Yaklaşan seans', (string) $stats['upcoming_sessions'], $y, 170);
    $pdf->row('Açık ödev', (string) $stats['open_actions'], $y, 170);
    $pdf->row('Açık risk uyarısı', (string) $openFlags, $y, 170);
    $y += 8;

    $pdf->heading('Öğrenci Listesi', $y);
    foreach ($students as $s) {
        $pdf->setFont(9);
        $flags = count(platform_get_risk_flags((int) $s['id'], 'open'));
        $pdf->bullet(
            $s['student_code'] . ' — ' . $s['first_name'] . ' ' . $s['last_name']
            . ' | ' . (platform_student_statuses()[$s['status']] ?? $s['status'])
            . ' | risk: ' . (platform_risk_levels()[$s['risk_level']] ?? $s['risk_level'])
            . ' | açık uyarı: ' . $flags,
            $y
        );
    }
    if (!$students) {
        $pdf->paragraph(56, $y, 'Kayıtlı öğrenci bulunmuyor.', 480, 13, 9);
    }

    $pdf->setFont(8);
    $pdf->line(48, 790, 547, 790, 0.4);
    $pdf->text(48, 804, 'Bu rapor koçluk yönetimi içindir. Gizli bilgi içerir.');
    platform_db_audit('report_generated', 'program', null, 'Genel program PDF raporu.');

    return 'goncalok-genel-rapor-' . date('Y-m-d') . '.pdf';
}
