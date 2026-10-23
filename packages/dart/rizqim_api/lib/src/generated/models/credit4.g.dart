// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'credit4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Credit4 _$Credit4FromJson(Map<String, dynamic> json) => Credit4(
  sales: Sales2.fromJson(json['sales'] as Map<String, dynamic>),
  repayments: Repayments.fromJson(json['repayments'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Credit4ToJson(Credit4 instance) => <String, dynamic>{
  'sales': instance.sales,
  'repayments': instance.repayments,
};
