// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'summary4.g.dart';

@JsonSerializable()
class Summary4 {
  const Summary4({
    required this.total,
    required this.waiting,
    required this.seated,
    required this.canceled,
    required this.noShow,
    required this.partySizeTotal,
    required this.avgQuotedWaitMinutes,
    required this.avgSeatedWaitMinutes,
    required this.longestWaitingMinutes,
  });

  factory Summary4.fromJson(Map<String, Object?> json) =>
      _$Summary4FromJson(json);

  final int total;
  final int waiting;
  final int seated;
  final int canceled;
  @JsonKey(name: 'no_show')
  final int noShow;
  @JsonKey(name: 'party_size_total')
  final int partySizeTotal;
  @JsonKey(name: 'avg_quoted_wait_minutes')
  final int? avgQuotedWaitMinutes;
  @JsonKey(name: 'avg_seated_wait_minutes')
  final int? avgSeatedWaitMinutes;
  @JsonKey(name: 'longest_waiting_minutes')
  final int? longestWaitingMinutes;

  Map<String, Object?> toJson() => _$Summary4ToJson(this);
}
