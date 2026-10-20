// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold_out.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SoldOut _$SoldOutFromJson(Map<String, dynamic> json) => SoldOut(
  variants: (json['variants'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
);

Map<String, dynamic> _$SoldOutToJson(SoldOut instance) => <String, dynamic>{
  'variants': instance.variants,
};
