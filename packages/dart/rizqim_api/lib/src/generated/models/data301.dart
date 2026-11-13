// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries7.dart';
import 'summary8.dart';

part 'data301.g.dart';

@JsonSerializable()
class Data301 {
  const Data301({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data301.fromJson(Map<String, Object?> json) =>
      _$Data301FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries7> entries;
  final Summary8 summary;

  Map<String, Object?> toJson() => _$Data301ToJson(this);
}
