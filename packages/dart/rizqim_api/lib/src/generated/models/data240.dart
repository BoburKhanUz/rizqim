// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'source.dart';
import 'shifts5.dart';
import 'rizqim_breakdown4.dart';
import 'transactions5.dart';
import 'unmatched4.dart';
import 'history4.dart';
import 'entered_by4.dart';

part 'data240.g.dart';

@JsonSerializable()
class Data240 {
  const Data240({
    required this.uuid,
    required this.shiftUuid,
    required this.zNumber,
    required this.branchId,
    required this.businessDate,
    required this.source,
    required this.terminalRef,
    required this.shared,
    required this.finalValue,
    required this.shifts,
    required this.bankTotal,
    required this.bankCount,
    required this.rizqimTotal,
    required this.rizqimCount,
    required this.rizqimBreakdown,
    required this.difference,
    required this.threshold,
    required this.overThreshold,
    required this.transactions,
    required this.unmatched,
    required this.history,
    required this.note,
    required this.enteredBy,
    required this.alertedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Data240.fromJson(Map<String, Object?> json) =>
      _$Data240FromJson(json);

  final String uuid;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'z_number')
  final int? zNumber;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'business_date')
  final String businessDate;
  final Source source;
  @JsonKey(name: 'terminal_ref')
  final String? terminalRef;
  final bool shared;

  /// The name has been replaced because it contains a keyword. Original name: `final`.
  @JsonKey(name: 'final')
  final bool finalValue;
  final List<Shifts5> shifts;
  @JsonKey(name: 'bank_total')
  final String bankTotal;
  @JsonKey(name: 'bank_count')
  final int? bankCount;
  @JsonKey(name: 'rizqim_total')
  final String? rizqimTotal;
  @JsonKey(name: 'rizqim_count')
  final int? rizqimCount;
  @JsonKey(name: 'rizqim_breakdown')
  final RizqimBreakdown4? rizqimBreakdown;
  final String? difference;
  final String? threshold;
  @JsonKey(name: 'over_threshold')
  final bool? overThreshold;
  final List<Transactions5>? transactions;
  final Unmatched4? unmatched;
  final List<History4> history;
  final String? note;
  @JsonKey(name: 'entered_by')
  final EnteredBy4? enteredBy;
  @JsonKey(name: 'alerted_at')
  final String? alertedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data240ToJson(this);
}
