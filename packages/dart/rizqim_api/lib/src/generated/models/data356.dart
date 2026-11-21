// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries13.dart';
import 'summary14.dart';

part 'data356.g.dart';

@JsonSerializable()
class Data356 {
  const Data356({
    required this.branchId,
    required this.date,
    required this.entries,
    required this.summary,
  });

  factory Data356.fromJson(Map<String, Object?> json) =>
      _$Data356FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String date;
  final List<Entries13> entries;
  final Summary14 summary;

  Map<String, Object?> toJson() => _$Data356ToJson(this);
}
