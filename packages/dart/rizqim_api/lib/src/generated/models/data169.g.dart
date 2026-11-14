// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data169.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data169 _$Data169FromJson(Map<String, dynamic> json) => Data169(
  voucher: Voucher.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data169ToJson(Data169 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
