// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_payment.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingPayment _$BookingPaymentFromJson(Map<String, dynamic> json) =>
    BookingPayment(
      id: (json['id'] as num).toInt(),
      uuid: json['uuid'] as String,
      bookingId: (json['booking_id'] as num).toInt(),
      storeId: (json['store_id'] as num).toInt(),
      branchId: (json['branch_id'] as num).toInt(),
      orderId: (json['order_id'] as num?)?.toInt(),
      amount: json['amount'] as String,
      tender: json['tender'] as String,
      cardType: json['card_type'] as String?,
      transactionId: json['transaction_id'] as String?,
      status: BookingPaymentStatus.fromJson(json['status'] as String),
      fiscalReceiptUuid: json['fiscal_receipt_uuid'] as String?,
      fiscalStatus: json['fiscal_status'] as String?,
      appliedAmount: json['applied_amount'] as String,
      appliedBillUuid: json['applied_bill_uuid'] as String?,
      applications: (json['applications'] as List<dynamic>?)
          ?.map((e) => Applications.fromJson(e as Map<String, dynamic>))
          .toList(),
      refundedAmount: json['refunded_amount'] as String,
      retainedAmount: json['retained_amount'] as String,
      settleReason: json['settle_reason'] as String?,
      settleReceiptUuid: json['settle_receipt_uuid'] as String?,
      settleFiscalStatus: json['settle_fiscal_status'] as String?,
      settledAt: json['settled_at'] == null
          ? null
          : DateTime.parse(json['settled_at'] as String),
      settledBy: (json['settled_by'] as num?)?.toInt(),
      approvedBy: (json['approved_by'] as num?)?.toInt(),
      deviceId: (json['device_id'] as num?)?.toInt(),
      userId: (json['user_id'] as num?)?.toInt(),
      idempotencyKey: json['idempotency_key'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      refundedDeviceId: (json['refunded_device_id'] as num?)?.toInt(),
      refundedShiftId: (json['refunded_shift_id'] as num?)?.toInt(),
      booking: json['booking'] == null
          ? null
          : Booking.fromJson(json['booking'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$BookingPaymentToJson(BookingPayment instance) =>
    <String, dynamic>{
      'id': instance.id,
      'uuid': instance.uuid,
      'booking_id': instance.bookingId,
      'store_id': instance.storeId,
      'branch_id': instance.branchId,
      'order_id': instance.orderId,
      'amount': instance.amount,
      'tender': instance.tender,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
      'status': instance.status,
      'fiscal_receipt_uuid': instance.fiscalReceiptUuid,
      'fiscal_status': instance.fiscalStatus,
      'applied_amount': instance.appliedAmount,
      'applied_bill_uuid': instance.appliedBillUuid,
      'applications': instance.applications,
      'refunded_amount': instance.refundedAmount,
      'retained_amount': instance.retainedAmount,
      'settle_reason': instance.settleReason,
      'settle_receipt_uuid': instance.settleReceiptUuid,
      'settle_fiscal_status': instance.settleFiscalStatus,
      'settled_at': instance.settledAt?.toIso8601String(),
      'settled_by': instance.settledBy,
      'approved_by': instance.approvedBy,
      'device_id': instance.deviceId,
      'user_id': instance.userId,
      'idempotency_key': instance.idempotencyKey,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'refunded_device_id': instance.refundedDeviceId,
      'refunded_shift_id': instance.refundedShiftId,
      'booking': instance.booking,
    };
