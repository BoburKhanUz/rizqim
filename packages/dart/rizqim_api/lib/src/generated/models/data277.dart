// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'summary7.dart';
import 'tips15.dart';

part 'data277.g.dart';

@JsonSerializable()
class Data277 {
  const Data277({
    required this.shiftUuid,
    required this.businessDate,
    required this.ownOnly,
    required this.summary,
    required this.tips,
  });

  factory Data277.fromJson(Map<String, Object?> json) =>
      _$Data277FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'own_only')
  final bool ownOnly;
  final Summary7 summary;
  final List<Tips15> tips;

  Map<String, Object?> toJson() => _$Data277ToJson(this);
}
