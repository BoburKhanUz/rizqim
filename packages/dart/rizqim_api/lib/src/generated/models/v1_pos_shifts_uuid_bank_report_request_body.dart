// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'source.dart';
import 'transactions6.dart';

part 'v1_pos_shifts_uuid_bank_report_request_body.g.dart';

@JsonSerializable()
class V1PosShiftsUuidBankReportRequestBody {
  const V1PosShiftsUuidBankReportRequestBody({
    this.source,
    this.bankTotal,
    this.bankCount,
    this.transactions,
    this.terminalRef,
    this.note,
  });

  factory V1PosShiftsUuidBankReportRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosShiftsUuidBankReportRequestBodyFromJson(json);

  /// manual | import | provider (manual by default).
  final Source? source;

  /// manual: the bank terminal's day total (decimal string).
  @JsonKey(name: 'bank_total')
  final String? bankTotal;

  /// manual: how many card payments the slip lists.
  @JsonKey(name: 'bank_count')
  final int? bankCount;

  /// import: the bank report's rows.
  final List<Transactions6>? transactions;

  /// The bank terminal's id or name (optional).
  @JsonKey(name: 'terminal_ref')
  final String? terminalRef;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1PosShiftsUuidBankReportRequestBodyToJson(this);
}
