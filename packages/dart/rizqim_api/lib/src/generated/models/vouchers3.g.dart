// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers3 _$Vouchers3FromJson(Map<String, dynamic> json) => Vouchers3(
  sold: Sold3.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed3.fromJson(json['redeemed'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Vouchers3ToJson(Vouchers3 instance) => <String, dynamic>{
  'sold': instance.sold,
  'redeemed': instance.redeemed,
};
