/**
 * Hand-built pages size things with Tailwind classes ('h-[15rem]', 'w-[35%]'); stored documents use
 * plain CSS values ('15rem', '35%') because Tailwind only generates classes it finds in source files.
 * Returns attrs for either, to be bound with v-bind (class and style merge with the element's own).
 */
export function sizeAttrs(value, classPrefix, cssProperty) {
  if (!value) return {}

  return value.startsWith(classPrefix) ? { class: value } : { style: { [cssProperty]: value } }
}
