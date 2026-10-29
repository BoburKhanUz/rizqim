// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unmatched3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unmatched3 _$Unmatched3FromJson(Map<String, dynamic> json) => Unmatched3(
  bank: (json['bank'] as List<dynamic>)
      .map((e) => Bank3.fromJson(e as Map<String, dynamic>))
      .toList(),
  rizqim: (json['rizqim'] as List<dynamic>)
      .map((e) => Rizqim3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Unmatched3ToJson(Unmatched3 instance) =>
    <String, dynamic>{'bank': instance.bank, 'rizqim': instance.rizqim};
