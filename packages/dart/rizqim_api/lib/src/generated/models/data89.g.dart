// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data89.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data89 _$Data89FromJson(Map<String, dynamic> json) => Data89(
  id: (json['id'] as num).toInt(),
  type: json['type'] as String,
  amount: json['amount'] as String,
  balanceBefore: json['balance_before'] as String,
  balanceAfter: json['balance_after'] as String,
  order: json['order'] == null
      ? null
      : Order2.fromJson(json['order'] as Map<String, dynamic>),
  referenceId: json['reference_id'] as String?,
  notes: json['notes'] as String?,
  createdAt: json['created_at'] as String,
);

Map<String, dynamic> _$Data89ToJson(Data89 instance) => <String, dynamic>{
  'id': instance.id,
  'type': instance.type,
  'amount': instance.amount,
  'balance_before': instance.balanceBefore,
  'balance_after': instance.balanceAfter,
  'order': instance.order,
  'reference_id': instance.referenceId,
  'notes': instance.notes,
  'created_at': instance.createdAt,
};
