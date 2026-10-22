// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposits.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposits _$DepositsFromJson(Map<String, dynamic> json) => Deposits(
  taken: Taken.fromJson(json['taken'] as Map<String, dynamic>),
  applied: Applied.fromJson(json['applied'] as Map<String, dynamic>),
  refunded: Refunded.fromJson(json['refunded'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DepositsToJson(Deposits instance) => <String, dynamic>{
  'taken': instance.taken,
  'applied': instance.applied,
  'refunded': instance.refunded,
};
