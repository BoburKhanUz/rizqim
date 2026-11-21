// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data172.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data172 _$Data172FromJson(Map<String, dynamic> json) => Data172(
  voucher: Voucher.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data172ToJson(Data172 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
