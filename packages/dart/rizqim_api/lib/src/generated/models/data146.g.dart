// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  voucher: Voucher.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
