// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'vouchers6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Vouchers6 _$Vouchers6FromJson(Map<String, dynamic> json) => Vouchers6(
  sold: Sold6.fromJson(json['sold'] as Map<String, dynamic>),
  redeemed: Redeemed6.fromJson(json['redeemed'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Vouchers6ToJson(Vouchers6 instance) => <String, dynamic>{
  'sold': instance.sold,
  'redeemed': instance.redeemed,
};
