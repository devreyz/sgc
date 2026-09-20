package br.rzin.sgc;

import android.content.Context;
import android.view.GestureDetector;
import android.view.MotionEvent;
import android.view.ScaleGestureDetector;
import android.view.View;
import android.view.ViewGroup;

/** A true two-axis PDF viewport with focal-point pinch zoom. */
public class PdfScrollView extends ViewGroup {
    public interface ZoomListener {
        void onZoomChanged(float zoom);
        void onGestureSettled(float zoom);
    }

    private final ScaleGestureDetector scaleDetector;
    private final GestureDetector gestureDetector;
    private ZoomListener zoomListener;
    private float zoom = 1f;
    private float anchorContentX;
    private float anchorContentY;
    private float anchorViewX;
    private float anchorViewY;
    private boolean scaling;

    public PdfScrollView(Context context) {
        super(context);
        setClickable(true);
        scaleDetector = new ScaleGestureDetector(context, new ScaleGestureDetector.SimpleOnScaleGestureListener() {
            @Override public boolean onScaleBegin(ScaleGestureDetector detector) {
                scaling = true;
                captureAnchor(detector.getFocusX(), detector.getFocusY());
                return true;
            }

            @Override public boolean onScale(ScaleGestureDetector detector) {
                anchorViewX = detector.getFocusX();
                anchorViewY = detector.getFocusY();
                setZoom(zoom * detector.getScaleFactor(), false);
                return true;
            }

            @Override public void onScaleEnd(ScaleGestureDetector detector) {
                scaling = false;
                if (zoomListener != null) zoomListener.onGestureSettled(zoom);
            }
        });
        gestureDetector = new GestureDetector(context, new GestureDetector.SimpleOnGestureListener() {
            @Override public boolean onDown(MotionEvent event) { return true; }

            @Override public boolean onScroll(MotionEvent first, MotionEvent current, float distanceX, float distanceY) {
                if (scaling) return true;
                scrollBy(Math.round(distanceX), Math.round(distanceY));
                return true;
            }

            @Override public boolean onDoubleTap(MotionEvent event) {
                captureAnchor(event.getX(), event.getY());
                setZoom(zoom > 1.05f ? 1f : 2f, true);
                return true;
            }
        });
    }

    public void setZoomListener(ZoomListener zoomListener) {
        this.zoomListener = zoomListener;
    }

    private void captureAnchor(float viewX, float viewY) {
        anchorViewX = viewX;
        anchorViewY = viewY;
        anchorContentX = (getScrollX() + viewX) / zoom;
        anchorContentY = (getScrollY() + viewY) / zoom;
    }

    private void setZoom(float value, boolean settle) {
        float next = Math.max(1f, Math.min(4f, value));
        if (Math.abs(next - zoom) < 0.005f) return;
        zoom = next;
        if (zoomListener != null) zoomListener.onZoomChanged(zoom);
        post(() -> scrollTo(
            Math.round(anchorContentX * zoom - anchorViewX),
            Math.round(anchorContentY * zoom - anchorViewY)
        ));
        if (settle && zoomListener != null) post(() -> zoomListener.onGestureSettled(zoom));
    }

    @Override public boolean onInterceptTouchEvent(MotionEvent event) {
        return true;
    }

    @Override public boolean onTouchEvent(MotionEvent event) {
        scaleDetector.onTouchEvent(event);
        gestureDetector.onTouchEvent(event);
        if (event.getActionMasked() == MotionEvent.ACTION_UP && !scaling && zoomListener != null) {
            zoomListener.onGestureSettled(zoom);
        }
        return true;
    }

    @Override protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
        int width = MeasureSpec.getSize(widthMeasureSpec);
        int height = MeasureSpec.getSize(heightMeasureSpec);
        if (getChildCount() > 0) {
            View child = getChildAt(0);
            int childWidth = Math.max(width, child.getLayoutParams().width);
            child.measure(MeasureSpec.makeMeasureSpec(childWidth, MeasureSpec.EXACTLY), MeasureSpec.makeMeasureSpec(0, MeasureSpec.UNSPECIFIED));
        }
        setMeasuredDimension(width, height);
    }

    @Override protected void onLayout(boolean changed, int left, int top, int right, int bottom) {
        if (getChildCount() == 0) return;
        View child = getChildAt(0);
        child.layout(0, 0, child.getMeasuredWidth(), child.getMeasuredHeight());
        scrollTo(getScrollX(), getScrollY());
    }

    @Override public void scrollTo(int x, int y) {
        View child = getChildCount() == 0 ? null : getChildAt(0);
        int maxX = child == null ? 0 : Math.max(0, child.getMeasuredWidth() - getWidth());
        int maxY = child == null ? 0 : Math.max(0, child.getMeasuredHeight() - getHeight());
        super.scrollTo(Math.max(0, Math.min(x, maxX)), Math.max(0, Math.min(y, maxY)));
    }
}
