// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposits4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposits4 _$Deposits4FromJson(Map<String, dynamic> json) => Deposits4(
  taken: Taken4.fromJson(json['taken'] as Map<String, dynamic>),
  applied: Applied4.fromJson(json['applied'] as Map<String, dynamic>),
  refunded: Refunded4.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Deposits4ToJson(Deposits4 instance) => <String, dynamic>{
  'taken': instance.taken,
  'applied': instance.applied,
  'refunded': instance.refunded,
};
