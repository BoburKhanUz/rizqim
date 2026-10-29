// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'checklist2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Checklist2 _$Checklist2FromJson(Map<String, dynamic> json) => Checklist2(
  oldKassaUnused: json['old_kassa_unused'] as bool?,
  bankTerminalMode: json['bank_terminal_mode'] as bool?,
  scanner2d: json['scanner_2d'] as bool?,
);

Map<String, dynamic> _$Checklist2ToJson(Checklist2 instance) =>
    <String, dynamic>{
      'old_kassa_unused': instance.oldKassaUnused,
      'bank_terminal_mode': instance.bankTerminalMode,
      'scanner_2d': instance.scanner2d,
    };
