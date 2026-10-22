// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposits5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposits5 _$Deposits5FromJson(Map<String, dynamic> json) => Deposits5(
  taken: Taken5.fromJson(json['taken'] as Map<String, dynamic>),
  applied: Applied5.fromJson(json['applied'] as Map<String, dynamic>),
  refunded: Refunded5.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Deposits5ToJson(Deposits5 instance) => <String, dynamic>{
  'taken': instance.taken,
  'applied': instance.applied,
  'refunded': instance.refunded,
};
