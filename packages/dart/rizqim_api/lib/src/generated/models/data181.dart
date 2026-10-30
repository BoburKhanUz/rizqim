// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries2.dart';
import 'summary4.dart';

part 'data181.g.dart';

@JsonSerializable()
class Data181 {
  const Data181({
    required this.branchId,
    required this.from,
    required this.to,
    required this.entries,
    required this.summary,
  });

  factory Data181.fromJson(Map<String, Object?> json) =>
      _$Data181FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Entries2> entries;
  final Summary4 summary;

  Map<String, Object?> toJson() => _$Data181ToJson(this);
}
