/* Existing reservations use the same floating Payment step as new checkout. */
(() => {
  const P=window.Player;
  P.openPaymentProof=(bookingId,paymentId)=>{
    P.closeModal();
    if(P.config.embedded)parent.postMessage({type:'pikol-open-payment',bookingId,paymentId},location.origin);
    else location.assign('../index.html?'+new URLSearchParams({'pay-booking':bookingId,...(paymentId?{payment:paymentId}:{})}));
  };
  P.actions['reservation-proof']=button=>P.openPaymentProof(button.dataset.id,button.dataset.payment);
  P.actions['submit-proof']=button=>{const payment=P.cache.payments?.items.find(p=>String(p.payment_id)===String(button.dataset.id));if(payment)return P.openPaymentProof(payment.booking_id,payment.payment_id);};
})();
