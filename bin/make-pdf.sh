#!/bin/bash

# Generate PDF from composer documentation.
# dependencies:
# * pandoc
# * latex

# abort on error
set -e

# use gsed if available
# gsed is part of the gnu-sed homebrew package on osx
if [[ -x "/usr/local/bin/gsed" ]]
then
    SED="/usr/local/bin/gsed"
else
    SED="sed"
fi

rm -rf cache/pdf
mkdir cache/pdf
mkdir cache/pdf/dev
mkdir cache/pdf/articles
mkdir cache/pdf/faqs

cd vendor/composer/composer/doc

for file in $(find . -type f -name '*.md' | sort | /bin/grep -v /fixtures)
do
    pandoc -o ../../../../cache/pdf/$(dirname $file)/$(basename $file .md).tex $file
done

cd ../../../../cache/pdf

cat > book.tmp <<EOF
\documentclass[letterpaper]{book}

\title{Composer}
\author{The Composer Community}

\usepackage[letterpaper,margin=1.5in]{geometry}
\usepackage{hyperref}
\usepackage{url}
\usepackage{enumerate}
\usepackage{listings}
\usepackage{microtype}
\usepackage[htt]{hyphenat}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage{textcomp}
\usepackage{tgpagella}
\usepackage{longtable}
\usepackage{framed,graphicx,xcolor}

\lstset{
    breaklines=true,
    basicstyle=\ttfamily
}

\raggedbottom

\begin{document}

\setlength{\parindent}{0cm}
\setlength{\parskip}{0.1cm}
\definecolor{shadecolor}{gray}{0.9}

\maketitle
\tableofcontents

\setlength{\parskip}{0.4cm}
EOF

cat *.tex >> book.tmp
mv book.tmp book.tex

# apply only to main part of book
$SED -i 's/\\section{/\\chapter{/g' book.tex
$SED -i 's/\\subsection{/\\section{/g' book.tex
$SED -i 's/\\subsubsection{/\\subsection{/g' book.tex
$SED -i '/←/d' book.tex
$SED -i '/→/d' book.tex
$SED -i 's/\\chapter{composer.json}/\\chapter[Schema]{composer.json}/g' book.tex
$SED -i 's/\\begin{longtable}\[c\]{@{}lll@{}}/\\begin{longtable}\[c\]{@{}p{\\dimexpr 0.2\\linewidth-2\\tabcolsep}p{\\dimexpr 0.2\\linewidth-2\\tabcolsep}p{\\dimexpr 0.6\\linewidth-2\\tabcolsep}@{}}/g' book.tex

echo "\chapter{Articles}" >> book.tex
cat articles/*.tex >> book.tex
echo "\chapter{FAQs}" >> book.tex
cat faqs/*.tex >> book.tex
echo >> book.tex
echo "\end{document}" >> book.tex

# apply to whole book
$SED -i 's/\\begin{verbatim}/\\begin{minipage}{\\textwidth} \\begin{lstlisting}/g' book.tex
$SED -i 's/\\end{verbatim}/\\end{lstlisting} \\end{minipage}/g' book.tex
$SED -i 's/\\textasciitilde{}/{\\raise.17ex\\hbox{$\\scriptstyle\\mathtt{\\sim}$}}/g' book.tex

# first run to build index, second run to render everything
pdflatex book.tex
pdflatex book.tex
pdflatex book.tex

mv book.pdf ../../web/
