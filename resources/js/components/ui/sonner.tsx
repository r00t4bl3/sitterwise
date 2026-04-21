import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
} from "lucide-react"
import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme()

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      icons={{
        success: <CircleCheckIcon className="size-8" />,
        info: <InfoIcon className="size-8" />,
        warning: <TriangleAlertIcon className="size-8" />,
        error: <OctagonXIcon className="size-8" />,
        loading: <Loader2Icon className="size-8 animate-spin" />,
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties
      }
      toastOptions={{
        // duration: 9000000, 
        unstyled: true,
        classNames: {
          icon: 'group-data-[type=error]:text-red-500 group-data-[type=success]:text-green-500 group-data-[type=warning]:text-amber-500 group-data-[type=info]:text-blue-500',
          toast: 'group text-sm rounded-lg p-5 min-w-64 border font-sans font-semibold toast group-[.toaster]:shadow-lg flex items-center gap-3',
    		  actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
		      cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
          success: 'group-[.toaster]:bg-emerald-100 group-[.toaster]:text-pale-green-100 group-[.toaster]:border-emerald-500 dark:group-[.toaster]:bg-emerald-500 dark:group-[.toaster]:border-emerald-500',
          error: 'group-[.toaster]:bg-red-300 group-[.toaster]:text-red-900 group-[.toaster]:border-red-200 dark:group-[.toaster]:bg-red-900 dark:group-[.toaster]:text-red-100 dark:group-[.toaster]:border-red-800',
          warning: 'group-[.toaster]:bg-orange-400 group-[.toaster]:text-white group-[.toaster]:border-orange-200 dark:group-[.toaster]:bg-orange-900 dark:group-[.toaster]:text-orange-100 dark:group-[.toaster]:border-orange-800',
          info: 'group-[.toaster]:bg-blue-100 group-[.toaster]:text-blue-900 group-[.toaster]:border-blue-200 dark:group-[.toaster]:bg-blue-900 dark:group-[.toaster]:text-blue-100 dark:group-[.toaster]:border-blue-800',
        }
      }}
      {...props}
    />
  )
}

export { Toaster }
