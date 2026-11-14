// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data351.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data351 _$Data351FromJson(Map<String, dynamic> json) => Data351(
  voucher: Voucher2.fromJson(json['voucher'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data351ToJson(Data351 instance) => <String, dynamic>{
  'voucher': instance.voucher,
  'entries': instance.entries,
};
