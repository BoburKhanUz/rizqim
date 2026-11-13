// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines4 _$PackagingLines4FromJson(Map<String, dynamic> json) =>
    PackagingLines4(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      amount: json['amount'] as String,
    );

Map<String, dynamic> _$PackagingLines4ToJson(PackagingLines4 instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
