// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unmatched.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unmatched _$UnmatchedFromJson(Map<String, dynamic> json) => Unmatched(
  bank: (json['bank'] as List<dynamic>)
      .map((e) => Bank.fromJson(e as Map<String, dynamic>))
      .toList(),
  rizqim: (json['rizqim'] as List<dynamic>)
      .map((e) => Rizqim.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$UnmatchedToJson(Unmatched instance) => <String, dynamic>{
  'bank': instance.bank,
  'rizqim': instance.rizqim,
};
