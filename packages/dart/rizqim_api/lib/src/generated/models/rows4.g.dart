// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows4 _$Rows4FromJson(Map<String, dynamic> json) => Rows4(
  paymentUuid: json['payment_uuid'] as String,
  kind: json['kind'] as String,
  tender: json['tender'] as String?,
  amount: json['amount'] as String,
  retain: json['retain'] as String,
  refund: json['refund'] as String,
  refundPath: json['refund_path'] as String?,
);

Map<String, dynamic> _$Rows4ToJson(Rows4 instance) => <String, dynamic>{
  'payment_uuid': instance.paymentUuid,
  'kind': instance.kind,
  'tender': instance.tender,
  'amount': instance.amount,
  'retain': instance.retain,
  'refund': instance.refund,
  'refund_path': instance.refundPath,
};
