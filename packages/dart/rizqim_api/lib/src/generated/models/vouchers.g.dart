// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers _$VouchersFromJson(Map<String, dynamic> json) => Vouchers(
  uuid: json['uuid'] as String,
  code: json['code'] as String,
  kind: json['kind'] as String,
  faceValue: json['face_value'] as String,
  balance: json['balance'] as String,
  currency: json['currency'] as String,
  validFrom: json['valid_from'] as String,
  validUntil: json['valid_until'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  branch: json['branch'] == null
      ? null
      : Branch9.fromJson(json['branch'] as Map<String, dynamic>),
  sale: Sale.fromJson(json['sale'] as Map<String, dynamic>),
  buyer: Buyer.fromJson(json['buyer'] as Map<String, dynamic>),
  note: json['note'] as String?,
  fiscal: Fiscal4.fromJson(json['fiscal'] as Map<String, dynamic>),
  blocked: json['blocked'] == null
      ? null
      : Blocked.fromJson(json['blocked'] as Map<String, dynamic>),
  print: json['print'] == null
      ? null
      : Print.fromJson(json['print'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$VouchersToJson(Vouchers instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'code': instance.code,
  'kind': instance.kind,
  'face_value': instance.faceValue,
  'balance': instance.balance,
  'currency': instance.currency,
  'valid_from': instance.validFrom,
  'valid_until': instance.validUntil,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'branch': instance.branch,
  'sale': instance.sale,
  'buyer': instance.buyer,
  'note': instance.note,
  'fiscal': instance.fiscal,
  'blocked': instance.blocked,
  'print': instance.print,
  'created_at': instance.createdAt,
};
