// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data144.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data144 _$Data144FromJson(Map<String, dynamic> json) => Data144(
  vouchers: (json['vouchers'] as List<dynamic>)
      .map((e) => Vouchers.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data144ToJson(Data144 instance) => <String, dynamic>{
  'vouchers': instance.vouchers,
  'meta': instance.meta,
};
