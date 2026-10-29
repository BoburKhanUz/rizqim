// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'history.g.dart';

@JsonSerializable()
class History {
  const History({
    required this.source,
    required this.bankTotal,
    required this.bankCount,
    required this.difference,
    required this.overThreshold,
    required this.enteredBy,
    required this.enteredAt,
  });

  factory History.fromJson(Map<String, Object?> json) =>
      _$HistoryFromJson(json);

  final String source;
  @JsonKey(name: 'bank_total')
  final String bankTotal;
  @JsonKey(name: 'bank_count')
  final int? bankCount;
  final String difference;
  @JsonKey(name: 'over_threshold')
  final bool overThreshold;
  @JsonKey(name: 'entered_by')
  final int? enteredBy;
  @JsonKey(name: 'entered_at')
  final String? enteredAt;

  Map<String, Object?> toJson() => _$HistoryToJson(this);
}
