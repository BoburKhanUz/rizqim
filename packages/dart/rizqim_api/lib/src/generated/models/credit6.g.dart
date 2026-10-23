// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'credit6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Credit6 _$Credit6FromJson(Map<String, dynamic> json) => Credit6(
  sales: Sales6.fromJson(json['sales'] as Map<String, dynamic>),
  repayments: Repayments3.fromJson(json['repayments'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Credit6ToJson(Credit6 instance) => <String, dynamic>{
  'sales': instance.sales,
  'repayments': instance.repayments,
};
