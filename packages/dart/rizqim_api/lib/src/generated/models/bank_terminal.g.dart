// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bank_terminal.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BankTerminal _$BankTerminalFromJson(Map<String, dynamic> json) => BankTerminal(
  uuid: json['uuid'] as String,
  shiftUuid: json['shift_uuid'] as String?,
  zNumber: (json['z_number'] as num?)?.toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  businessDate: json['business_date'] as String,
  source: Source.fromJson(json['source'] as String),
  terminalRef: json['terminal_ref'] as String?,
  shared: json['shared'] as bool,
  finalValue: json['final'] as bool,
  shifts: (json['shifts'] as List<dynamic>)
      .map((e) => Shifts4.fromJson(e as Map<String, dynamic>))
      .toList(),
  bankTotal: json['bank_total'] as String,
  bankCount: (json['bank_count'] as num?)?.toInt(),
  rizqimTotal: json['rizqim_total'] as String?,
  rizqimCount: (json['rizqim_count'] as num?)?.toInt(),
  rizqimBreakdown: json['rizqim_breakdown'] == null
      ? null
      : RizqimBreakdown3.fromJson(
          json['rizqim_breakdown'] as Map<String, dynamic>,
        ),
  difference: json['difference'] as String?,
  threshold: json['threshold'] as String?,
  overThreshold: json['over_threshold'] as bool?,
  transactions: (json['transactions'] as List<dynamic>?)
      ?.map((e) => Transactions4.fromJson(e as Map<String, dynamic>))
      .toList(),
  unmatched: json['unmatched'] == null
      ? null
      : Unmatched3.fromJson(json['unmatched'] as Map<String, dynamic>),
  history: (json['history'] as List<dynamic>)
      .map((e) => History3.fromJson(e as Map<String, dynamic>))
      .toList(),
  note: json['note'] as String?,
  enteredBy: json['entered_by'] == null
      ? null
      : EnteredBy3.fromJson(json['entered_by'] as Map<String, dynamic>),
  alertedAt: json['alerted_at'] as String?,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$BankTerminalToJson(BankTerminal instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'shift_uuid': instance.shiftUuid,
      'z_number': instance.zNumber,
      'branch_id': instance.branchId,
      'business_date': instance.businessDate,
      'source': instance.source,
      'terminal_ref': instance.terminalRef,
      'shared': instance.shared,
      'final': instance.finalValue,
      'shifts': instance.shifts,
      'bank_total': instance.bankTotal,
      'bank_count': instance.bankCount,
      'rizqim_total': instance.rizqimTotal,
      'rizqim_count': instance.rizqimCount,
      'rizqim_breakdown': instance.rizqimBreakdown,
      'difference': instance.difference,
      'threshold': instance.threshold,
      'over_threshold': instance.overThreshold,
      'transactions': instance.transactions,
      'unmatched': instance.unmatched,
      'history': instance.history,
      'note': instance.note,
      'entered_by': instance.enteredBy,
      'alerted_at': instance.alertedAt,
      'created_at': instance.createdAt,
      'updated_at': instance.updatedAt,
    };
