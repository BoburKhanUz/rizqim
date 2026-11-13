// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers2 _$Vouchers2FromJson(Map<String, dynamic> json) => Vouchers2(
  sold: Sold2.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed2.fromJson(json['redeemed'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Vouchers2ToJson(Vouchers2 instance) => <String, dynamic>{
  'sold': instance.sold,
  'redeemed': instance.redeemed,
};
