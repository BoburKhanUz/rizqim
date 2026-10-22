// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'credit5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Credit5 _$Credit5FromJson(Map<String, dynamic> json) => Credit5(
  sales: Sales4.fromJson(json['sales'] as Map<String, dynamic>),
  repayments: Repayments2.fromJson(json['repayments'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Credit5ToJson(Credit5 instance) => <String, dynamic>{
  'sales': instance.sales,
  'repayments': instance.repayments,
};
