// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unmatched2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unmatched2 _$Unmatched2FromJson(Map<String, dynamic> json) => Unmatched2(
  bank: (json['bank'] as List<dynamic>)
      .map((e) => Bank2.fromJson(e as Map<String, dynamic>))
      .toList(),
  rizqim: (json['rizqim'] as List<dynamic>)
      .map((e) => Rizqim2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Unmatched2ToJson(Unmatched2 instance) =>
    <String, dynamic>{'bank': instance.bank, 'rizqim': instance.rizqim};
