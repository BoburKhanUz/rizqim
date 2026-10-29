// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bank_terminal3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BankTerminal3 _$BankTerminal3FromJson(Map<String, dynamic> json) =>
    BankTerminal3(
      total: json['total'] as num?,
      count: (json['count'] as num?)?.toInt(),
      terminalRef: json['terminal_ref'] as String?,
      note: json['note'] as String?,
    );

Map<String, dynamic> _$BankTerminal3ToJson(BankTerminal3 instance) =>
    <String, dynamic>{
      'total': instance.total,
      'count': instance.count,
      'terminal_ref': instance.terminalRef,
      'note': instance.note,
    };
