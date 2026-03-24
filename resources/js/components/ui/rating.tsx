import * as React from "react"

import { cn } from "@/lib/utils"

interface RatingProps extends React.HTMLAttributes<HTMLDivElement> {
  value: number
  max?: number
  showScore?: boolean
  size?: "sm" | "md" | "lg"
}

function Rating({
  value,
  max = 5,
  showScore = true,
  size = "md",
  className,
  ...props
}: RatingProps) {
  const numericValue = typeof value === 'number' ? value : parseFloat(value) || 0

  const sizeClasses = {
    sm: "h-3 w-3",
    md: "h-4 w-4",
    lg: "h-5 w-5",
  }

  const textSizeClasses = {
    sm: "text-xs",
    md: "text-sm",
    lg: "text-base",
  }

  return (
    <div className={cn("flex items-center gap-1", className)} {...props}>
      <div className="flex items-center gap-0.5">
        {Array.from({ length: max }).map((_, index) => {
          const filled = index < Math.floor(numericValue)
          const halfFilled = !filled && index < numericValue

          return (
            <StarIcon
              key={index}
              className={cn(
                sizeClasses[size],
                filled
                  ? "fill-amber-400 text-amber-400"
                  : halfFilled
                  ? "fill-amber-400/50 text-amber-400"
                  : "fill-muted text-muted"
              )}
            />
          )
        })}
      </div>
      {showScore && (
        <span
          className={cn(
            "font-medium text-foreground",
            textSizeClasses[size]
          )}
        >
          {numericValue.toFixed(1)}
        </span>
      )}
    </div>
  )
}

function StarIcon({ className, ...props }: React.SVGProps<SVGSVGElement>) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      {...props}
    >
      <polygon
        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
        fill="currentColor"
      />
    </svg>
  )
}

export { Rating, StarIcon }