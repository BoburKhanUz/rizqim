// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'terminals.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Terminals _$TerminalsFromJson(Map<String, dynamic> json) => Terminals(
  activeNow: (json['active_now'] as num).toInt(),
  counted: (json['counted'] as num?)?.toInt(),
);

Map<String, dynamic> _$TerminalsToJson(Terminals instance) => <String, dynamic>{
  'active_now': instance.activeNow,
  'counted': instance.counted,
};
