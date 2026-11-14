// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guests.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Guests _$GuestsFromJson(Map<String, dynamic> json) => Guests(
  guaranteed: (json['guaranteed'] as num).toInt(),
  minimum: (json['minimum'] as num).toInt(),
  actual: (json['actual'] as num?)?.toInt(),
  billed: (json['billed'] as num).toInt(),
);

Map<String, dynamic> _$GuestsToJson(Guests instance) => <String, dynamic>{
  'guaranteed': instance.guaranteed,
  'minimum': instance.minimum,
  'actual': instance.actual,
  'billed': instance.billed,
};
