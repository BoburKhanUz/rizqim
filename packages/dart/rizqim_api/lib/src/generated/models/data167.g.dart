// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data167.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data167 _$Data167FromJson(Map<String, dynamic> json) => Data167(
  vouchers: (json['vouchers'] as List<dynamic>)
      .map((e) => Vouchers.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data167ToJson(Data167 instance) => <String, dynamic>{
  'vouchers': instance.vouchers,
  'meta': instance.meta,
};
