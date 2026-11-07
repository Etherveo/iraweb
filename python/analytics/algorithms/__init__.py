"""Modul algoritma Trend Analytics (referensi, stdlib saja).

Fungsi-fungsi murni: terima data fakta + parameter, kembalikan dict hasil terstruktur.
Pembungkus HTTP, akses database, dan kode Laravel BUKAN bagian paket ini.
"""

from ._common import ALGORITHM_VERSION, AnalyticsInputError
from .category import analyze_category, compare_categories
from .compare import compare_metric, compare_periods
from .funnel import analyze_funnel, dedup_views
from .insight import build_insights
from .interest import analyze_interest, wilson_interval
from .summary import summarize
from .trend import analyze_trend

__all__ = [
    "ALGORITHM_VERSION", "AnalyticsInputError",
    "analyze_trend", "analyze_funnel", "compare_periods", "compare_metric",
    "analyze_interest", "wilson_interval", "analyze_category", "compare_categories",
    "summarize", "build_insights", "dedup_views",
]
