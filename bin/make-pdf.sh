#!/bin/bash

# Generate PDF from composer documentation.
# dependencies:
# * pandoc
# * latex

rm -rf cache/pdf
mkdir cache/pdf
mkdir cache/pdf/dev
mkdir cache/pdf/articles
mkdir cache/pdf/faqs

cd vendor/composer/composer/doc

for file in $(find . -type f -name '*.md')
do
    pandoc -o ../../../../cache/pdf/$(dirname $file)/$(basename $file .md).tex $file
done

cd ../../../../cache/pdf

cat > book.tex <<EOF
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
\usepackage{lmodern}
\usepackage{textcomp}

\lstset{
    breaklines=true,
    basicstyle=\ttfamily
}

\raggedbottom

\begin{document}

\setlength{\parindent}{0cm}
\setlength{\parskip}{0.1cm}

\maketitle
\tableofcontents

\setlength{\parskip}{0.4cm}
EOF

cat *.tex >> book.tex

# apply only to main part of book
sed -i 's/\\section{/\\chapter{/g' book.tex
sed -i 's/\\subsection{/\\section{/g' book.tex
sed -i 's/\\subsubsection{/\\subsection{/g' book.tex
sed -i '/←/d' book.tex
sed -i '/→/d' book.tex
sed -i 's/\\chapter{composer.json}/\\chapter[Schema]{composer.json}/g' book.tex

echo "\chapter{Articles}" >> book.tex
cat articles/*.tex >> book.tex
echo "\chapter{FAQs}" >> book.tex
cat faqs/*.tex >> book.tex
echo >> book.tex
echo "\end{document}" >> book.tex

# apply to whole book
sed -i 's/\\begin{verbatim}/\\begin{minipage}{\\textwidth} \\begin{lstlisting}/g' book.tex
sed -i 's/\\end{verbatim}/\\end{lstlisting} \\end{minipage}/g' book.tex
sed -i 's/\\textasciitilde{}/{\\raise.17ex\\hbox{$\\scriptstyle\\mathtt{\\sim}$}}/g' book.tex

pdflatex book.tex

mv book.pdf ../../web/
