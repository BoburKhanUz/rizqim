// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data174.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data174 _$Data174FromJson(Map<String, dynamic> json) => Data174(
  voucher: Voucher.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data174ToJson(Data174 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
