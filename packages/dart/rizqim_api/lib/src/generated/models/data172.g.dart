// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data172.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data172 _$Data172FromJson(Map<String, dynamic> json) => Data172(
  vouchers: (json['vouchers'] as List<dynamic>)
      .map((e) => Vouchers.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data172ToJson(Data172 instance) => <String, dynamic>{
  'vouchers': instance.vouchers,
  'meta': instance.meta,
};
