// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order8 _$Order8FromJson(Map<String, dynamic> json) => Order8(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  paymentStatus: json['payment_status'] as String,
  version: (json['version'] as num).toInt(),
  queueNumber: (json['queue_number'] as num?)?.toInt(),
  businessDate: json['business_date'] as String?,
  billLockedAt: json['bill_locked_at'] as String?,
  closedAt: json['closed_at'] as String?,
  totalPrice: json['total_price'] as String,
  billedAmount: json['billed_amount'] as String,
  paidAmount: json['paid_amount'] as String,
  unbilledAmount: json['unbilled_amount'] as String?,
  fiscalPending: json['fiscal_pending'] as bool,
  billsOutOfDate: json['bills_out_of_date'] as bool,
);

Map<String, dynamic> _$Order8ToJson(Order8 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'payment_status': instance.paymentStatus,
  'version': instance.version,
  'queue_number': instance.queueNumber,
  'business_date': instance.businessDate,
  'bill_locked_at': instance.billLockedAt,
  'closed_at': instance.closedAt,
  'total_price': instance.totalPrice,
  'billed_amount': instance.billedAmount,
  'paid_amount': instance.paidAmount,
  'unbilled_amount': instance.unbilledAmount,
  'fiscal_pending': instance.fiscalPending,
  'bills_out_of_date': instance.billsOutOfDate,
};
