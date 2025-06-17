<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: index.html');
    exit();
}

// Get form data
$studentName = $_POST['student_name'] ?? '';
$rollNumber = $_POST['roll_number'] ?? '';
$prnNumber = $_POST['prn_number'] ?? '';
$division = $_POST['division'] ?? '';
$branch = $_POST['branch'] ?? '';
$year = $_POST['year'] ?? '';
$marksData = [];
$subjects = json_decode($_POST['subjects'] ?? '[]', true);
$totalObtained = $_POST['total_obtained'] ?? 0;
$totalMax = $_POST['total_max'] ?? 0;
$percentage = $_POST['percentage'] ?? 0;

// Parse marks data
foreach ($_POST['marks'] ?? [] as $index => $mark) {
    $marksData[] = [
        'subject' => $mark['subject'],
        'unit_test_1' => $mark['unit_test_1'],
        'unit_test_2' => $mark['unit_test_2'],
        'end_semester' => $mark['end_semester'],
        'practical' => $mark['practical'],
        'total' => $mark['total'],
        'pass_status' => $mark['pass_status']
    ];
}

if (empty($marksData)) {
    die("Error: No marks data available to generate PDF.");
}

// Generate LaTeX content
ob_start();
?>
\documentclass[a4paper,12pt]{article}
\usepackage{geometry}
\geometry{margin=1in}
\usepackage{tabularx}
\usepackage{fancyhdr}
\usepackage{titlesec}
\usepackage{array}
\usepackage{longtable}
\usepackage{graphicx}
\usepackage{float}

\pagestyle{fancy}
\fancyhf{}
\fancyhead[C]{\textbf{Karmaveer Bhaurao Patil College of Engineering, Satara}}
\fancyfoot[C]{\thepage}

\renewcommand{\arraystretch}{1.2}

\begin{document}

\begin{center}
    {\Large \textbf{Karmaveer Bhaurao Patil College of Engineering, Satara}}\\[4pt]
    {\large \textbf{Student Marksheet}}\\[2pt]
    Academic Year: 2024--2025
\end{center}

\vspace{0.5cm}

\noindent
\textbf{Student Information}\\
\begin{tabular}{@{}ll}
\textbf{Name:} & <?php echo $studentName; ?> \\
\textbf{Roll Number:} & <?php echo $rollNumber; ?> \\
\textbf{PRN Number:} & <?php echo $prnNumber; ?> \\
\textbf{Division:} & <?php echo $division; ?> \\
\textbf{Branch:} & <?php echo $branch; ?> \\
\textbf{Year:} & <?php echo $year; ?> \\
\end{tabular}

\vspace{0.8cm}

\noindent
\textbf{Marksheet}\\

\begin{longtable}{|>{\centering\arraybackslash}m{3.3cm}|>{\centering\arraybackslash}m{2cm}|>{\centering\arraybackslash}m{2cm}|>{\centering\arraybackslash}m{2.5cm}|>{\centering\arraybackslash}m{2cm}|>{\centering\arraybackslash}m{2cm}|>{\centering\arraybackslash}m{2cm}|}
\hline
\textbf{Subject} & \textbf{UT1 (20)} & \textbf{UT2 (20)} & \textbf{End Sem (100)} & \textbf{Practical (50)} & \textbf{Total (190)} & \textbf{Status} \\
\hline
\endfirsthead
\hline
\textbf{Subject} & \textbf{UT1 (20)} & \textbf{UT2 (20)} & \textbf{End Sem (100)} & \textbf{Practical (50)} & \textbf{Total (190)} & \textbf{Status} \\
\hline
\endhead
<?php foreach ($marksData as $mark): ?>
<?php echo $mark['subject']; ?> & 
<?php echo $mark['unit_test_1']; ?> & 
<?php echo $mark['unit_test_2']; ?> & 
<?php echo $mark['end_semester']; ?> & 
<?php echo $mark['practical']; ?> & 
<?php echo $mark['total']; ?> & 
<?php echo $mark['pass_status']; ?> \\
\hline
<?php endforeach; ?>

\multicolumn{5}{|r|}{\textbf{Total:}} & \textbf{<?php echo $totalObtained; ?> / <?php echo $totalMax; ?>} & \\
\hline
\multicolumn{5}{|r|}{\textbf{Percentage:}} & \multicolumn{2}{c|}{\textbf{<?php echo number_format($percentage, 2); ?>\%}} \\
\hline
\end{longtable}

\vspace{1cm}
\begin{center}
    \textit{Generated on: <?php echo date('d F Y'); ?>}
\end{center}

\end{document}
<?php
$latexContent = ob_get_clean();


// Create a temporary directory for LaTeX processing
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'latex_' . uniqid();
if (!mkdir($tempDir, 0755, true)) {
    die("Error: Could not create temporary directory.");
}

// Write LaTeX content to a .tex file
$texFile = $tempDir . DIRECTORY_SEPARATOR . 'marksheet.tex';
if (!file_put_contents($texFile, $latexContent)) {
    die("Error: Could not write LaTeX file.");
}

// Run pdflatex to generate the PDF
// Use the full path to pdflatex.exe provided by the user
$pdflatexPath = '"C:\Users\Kunal\AppData\Local\Programs\MiKTeX\miktex\bin\x64\pdflatex.exe"';
$pdflatexCommand = "$pdflatexPath -interaction=nonstopmode -output-directory=\"$tempDir\" \"$texFile\" 2>&1";
$output = shell_exec($pdflatexCommand);

// Check if pdflatex ran successfully
$pdfFile = $tempDir . DIRECTORY_SEPARATOR . 'marksheet.pdf';
if (!file_exists($pdfFile)) {
    // Clean up temporary files
    array_map('unlink', glob("$tempDir/*"));
    rmdir($tempDir);
    die("Error: PDF generation failed. pdflatex output: " . htmlspecialchars($output));
}

// Send the PDF to the browser for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="marksheet.pdf"');
header('Content-Length: ' . filesize($pdfFile));
readfile($pdfFile);

// Clean up temporary files
array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);

exit();
?>