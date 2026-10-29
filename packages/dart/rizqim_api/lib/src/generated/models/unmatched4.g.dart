// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unmatched4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unmatched4 _$Unmatched4FromJson(Map<String, dynamic> json) => Unmatched4(
  bank: (json['bank'] as List<dynamic>)
      .map((e) => Bank4.fromJson(e as Map<String, dynamic>))
      .toList(),
  rizqim: (json['rizqim'] as List<dynamic>)
      .map((e) => Rizqim4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Unmatched4ToJson(Unmatched4 instance) =>
    <String, dynamic>{'bank': instance.bank, 'rizqim': instance.rizqim};
