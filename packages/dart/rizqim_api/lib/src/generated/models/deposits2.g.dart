// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposits2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposits2 _$Deposits2FromJson(Map<String, dynamic> json) => Deposits2(
  taken: Taken2.fromJson(json['taken'] as Map<String, dynamic>),
  applied: Applied2.fromJson(json['applied'] as Map<String, dynamic>),
  refunded: Refunded2.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Deposits2ToJson(Deposits2 instance) => <String, dynamic>{
  'taken': instance.taken,
  'applied': instance.applied,
  'refunded': instance.refunded,
};
