// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'credit8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Credit8 _$Credit8FromJson(Map<String, dynamic> json) => Credit8(
  sales: Sales10.fromJson(json['sales'] as Map<String, dynamic>),
  repayments: Repayments5.fromJson(json['repayments'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Credit8ToJson(Credit8 instance) => <String, dynamic>{
  'sales': instance.sales,
  'repayments': instance.repayments,
};
