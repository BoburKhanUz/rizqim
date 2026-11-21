// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entries10.dart';

part 'data283.g.dart';

@JsonSerializable()
class Data283 {
  const Data283({required this.account, required this.entries});

  factory Data283.fromJson(Map<String, Object?> json) =>
      _$Data283FromJson(json);

  final Account2 account;
  final List<Entries10> entries;

  Map<String, Object?> toJson() => _$Data283ToJson(this);
}
