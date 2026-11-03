// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines11 _$PackagingLines11FromJson(Map<String, dynamic> json) =>
    PackagingLines11(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      amount: json['amount'] as String,
    );

Map<String, dynamic> _$PackagingLines11ToJson(PackagingLines11 instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
