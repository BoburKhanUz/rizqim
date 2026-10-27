// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
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

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
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
