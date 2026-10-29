// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'transactions3.dart';
import 'source.dart';

part 'v1_owner_fiscal_bank_reports_uuid_request_body.g.dart';

@JsonSerializable()
class V1OwnerFiscalBankReportsUuidRequestBody {
  const V1OwnerFiscalBankReportsUuidRequestBody({
    this.transactions,
    this.source,
    this.bankTotal,
    this.bankCount,
    this.terminalRef,
    this.note,
  });

  factory V1OwnerFiscalBankReportsUuidRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerFiscalBankReportsUuidRequestBodyFromJson(json);

  /// import: the bank report's rows.
  final List<Transactions3>? transactions;

  /// manual | import | provider (manual by default).
  final Source? source;

  /// manual: the bank terminal's day total (decimal string).
  @JsonKey(name: 'bank_total')
  final String? bankTotal;

  /// manual: how many card payments the slip lists.
  @JsonKey(name: 'bank_count')
  final int? bankCount;

  /// The bank terminal's id or name (optional).
  @JsonKey(name: 'terminal_ref')
  final String? terminalRef;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1OwnerFiscalBankReportsUuidRequestBodyToJson(this);
}
