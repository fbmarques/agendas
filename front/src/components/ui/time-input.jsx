import { forwardRef } from "react";
import { Input } from "@/components/ui/input";

/**
 * Input de horário em formato 24h (HH:MM), independente do locale do SO.
 * Digita apenas números; o `:` é inserido automaticamente e a hora é
 * validada (00-23) e o minuto (00-59) em tempo real. Ao perder o foco,
 * pads para 2 dígitos em cada lado (ex.: "5:3" -> "05:03").
 */
const TimeInput = forwardRef(({ value = "", onChange, onBlur, className, ...props }, ref) => {
  const emit = (v) => onChange?.({ target: { value: v } });

  const handleChange = (e) => {
    const digits = e.target.value.replace(/\D/g, "").slice(0, 4);

    if (digits.length >= 2 && parseInt(digits.slice(0, 2), 10) > 23) return;
    if (digits.length >= 4 && parseInt(digits.slice(2), 10) > 59) return;

    const formatted = digits.length > 2 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits;
    emit(formatted);
  };

  const handleBlur = (e) => {
    const digits = e.target.value.replace(/\D/g, "");
    if (digits.length) {
      const padded = digits.padStart(4, "0").slice(0, 4);
      emit(`${padded.slice(0, 2)}:${padded.slice(2)}`);
    }
    onBlur?.(e);
  };

  return (
    <Input
      type="text"
      inputMode="numeric"
      placeholder="HH:MM"
      maxLength={5}
      value={value ?? ""}
      onChange={handleChange}
      onBlur={handleBlur}
      className={className}
      ref={ref}
      {...props}
    />
  );
});

TimeInput.displayName = "TimeInput";

export { TimeInput };
