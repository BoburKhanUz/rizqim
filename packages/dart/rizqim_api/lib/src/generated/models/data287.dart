// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries10.dart';

part 'data287.g.dart';

@JsonSerializable()
class Data287 {
  const Data287({required this.account, required this.entries});

  factory Data287.fromJson(Map<String, Object?> json) =>
      _$Data287FromJson(json);

  final Account2 account;
  final List<Entries10> entries;

  Map<String, Object?> toJson() => _$Data287ToJson(this);
}
