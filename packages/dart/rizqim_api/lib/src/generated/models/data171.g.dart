// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data171.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data171 _$Data171FromJson(Map<String, dynamic> json) => Data171(
  voucher: Voucher.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data171ToJson(Data171 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
