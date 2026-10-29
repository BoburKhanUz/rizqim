// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unmatched5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unmatched5 _$Unmatched5FromJson(Map<String, dynamic> json) => Unmatched5(
  bank: (json['bank'] as List<dynamic>)
      .map((e) => Bank5.fromJson(e as Map<String, dynamic>))
      .toList(),
  rizqim: (json['rizqim'] as List<dynamic>)
      .map((e) => Rizqim5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Unmatched5ToJson(Unmatched5 instance) =>
    <String, dynamic>{'bank': instance.bank, 'rizqim': instance.rizqim};
