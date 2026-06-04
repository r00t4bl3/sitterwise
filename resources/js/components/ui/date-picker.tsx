"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface DatePickerProps {
  value?: string
  onChange?: (date: string) => void
  placeholder?: string
  name?: string
  fromYear?: number
  toYear?: number
  disabled?: { before: Date } | { after: Date }
  defaultMonth?: Date
}

export function DatePicker({
  value,
  onChange,
  placeholder = "Pick a date",
  name,
  fromYear,
  toYear,
  disabled,
  defaultMonth,
}: DatePickerProps) {
  const parseDate = React.useCallback((dateStr: string | null | undefined): Date | null => {
    if (!dateStr) return null
    const parts = dateStr.split('-').map(Number)
    if (parts.length !== 3 || parts.some(isNaN)) return null
    const d = new Date(parts[0], parts[1] - 1, parts[2])
    if (isNaN(d.getTime())) return null
    return d
  }, [])

  const startMonth = fromYear ? new Date(fromYear, 0) : undefined
  const endMonth = toYear ? new Date(toYear, 11) : undefined
  const [date, setDate] = React.useState<Date | undefined>(
    value ? parseDate(value) ?? undefined : undefined
  )
  const [open, setOpen] = React.useState(false)

  React.useEffect(() => {
    if (value) {
      setDate(parseDate(value) ?? undefined)
    }
  }, [value])

  const handleSelect = (selectedDate: Date | undefined) => {
    setDate(selectedDate)
    if (selectedDate && onChange) {
      onChange(format(selectedDate, "yyyy-MM-dd"))
    }
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          data-empty={!date}
          className="w-full justify-start text-left font-normal data-[empty=true]:text-muted-foreground"
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {date ? format(date, "PPP") : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={handleSelect}
          defaultMonth={defaultMonth ?? date}
          captionLayout="dropdown"
          startMonth={startMonth}
          endMonth={endMonth}
          disabled={disabled}
        />
      </PopoverContent>
      {name && (
        <input type="hidden" name={name} value={value || ""} />
      )}
    </Popover>
  )
}