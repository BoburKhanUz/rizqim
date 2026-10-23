// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposits3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposits3 _$Deposits3FromJson(Map<String, dynamic> json) => Deposits3(
  taken: Taken3.fromJson(json['taken'] as Map<String, dynamic>),
  applied: Applied3.fromJson(json['applied'] as Map<String, dynamic>),
  refunded: Refunded3.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Deposits3ToJson(Deposits3 instance) => <String, dynamic>{
  'taken': instance.taken,
  'applied': instance.applied,
  'refunded': instance.refunded,
};
