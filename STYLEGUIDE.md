* The delinter is authoritative and captures most formatting questions, but below guidance should also be followed
* Line length <= 110 for code in all languages, unless very strong readability case or literally impossible
* Function signatures, function calls and array literals can and should be on one line unless either (a) strong
  readability case not to or (b) line length limit would be exceeded. In which case, go with one
  param per line.
* Prefer `\explode`, `\Exception` etc. The leading backslash makes global namespace obvious and has
  an (admittedly minuscule) performance benefit. For non-globals, use `use` (if necessary with
  alias) at top of file rather than inlining fully qualified names. This includes inside docblocks
  where referenced via `{@see TheThing}`.
* Docblocks can and should be on one line e.g. `/** A sentence here. */` if they're simple (e.g.
  only documenting one thing, not multiple parameters or etc.) and don't exceed the line length
  limit.
* Redundant parentheses should be used in complex expressions to clarify precedence for readers of
  the code.
* Redundant parentheses should be used to clarify relative precedence of "adjacent" boolean operations as in:
  `$a = $b || $c && $d`.
* Redundant parentheses should be used for readability of expressions in which `=`/`==`/`===` are
  "adjacent", e.g. `$a = $b === $c` should be `$a = ($b === $c)`.
* Equality:
    * Use `===`/`!==` as a general rule.
    * To avoid "surprising inequalities" (e.g. `3` vs `3.0`), use `==`/`!=` when comparing numbers.
    * Departure from these rules is permissible if there's a pressing reason; in which case the
      reason should be commented.
