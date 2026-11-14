// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments4 _$Payments4FromJson(Map<String, dynamic> json) => Payments4(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  dueDate: json['due_date'] as String?,
  amount: json['amount'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  tender: json['tender'] as String?,
  paidAt: json['paid_at'] as String?,
  fiscalReceiptUuid: json['fiscal_receipt_uuid'] as String?,
);

Map<String, dynamic> _$Payments4ToJson(Payments4 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'due_date': instance.dueDate,
  'amount': instance.amount,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'tender': instance.tender,
  'paid_at': instance.paidAt,
  'fiscal_receipt_uuid': instance.fiscalReceiptUuid,
};
