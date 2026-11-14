// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'packaging_lines.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PackagingLines _$PackagingLinesFromJson(Map<String, dynamic> json) =>
    PackagingLines(
      ruleId: (json['rule_id'] as num).toInt(),
      name: json['name'] as String,
      quantity: (json['quantity'] as num).toInt(),
      unit: json['unit'] as String,
      amount: json['amount'] as String,
    );

Map<String, dynamic> _$PackagingLinesToJson(PackagingLines instance) =>
    <String, dynamic>{
      'rule_id': instance.ruleId,
      'name': instance.name,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'amount': instance.amount,
    };
