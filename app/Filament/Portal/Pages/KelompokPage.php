<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KelompokPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Kelompok Saya';

    protected static ?string $title = 'Kelompok Saya';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    protected array $searchableColumns = ['name', 'course_name', 'leader_name', 'member_names'];

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $userId = (string) auth()->id();

                $roles = app(GroupMemberRepository::class)
                    ->forUser($userId)
                    ->mapWithKeys(fn (array $member): array => [
                        (string) ($member['group_id'] ?? '') => (string) ($member['role'] ?? 'anggota'),
                    ]);

                $records = app(GroupRepository::class)
                    ->withDetails(app(GroupRepository::class)->forStudent($userId))
                    ->map(function (array $group) use ($roles): array {
                        $group['my_role'] = $roles->get((string) $group['id']) === 'ketua'
                            ? 'Ketua'
                            : 'Anggota';

                        return $group;
                    });

                return $this->paginateRecords(
                    $records,
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Kelompok')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('course_name')
                    ->label('Mata kuliah')
                    ->searchable(),
                TextColumn::make('my_role')
                    ->label('Peran saya')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ketua' ? 'success' : 'gray'),
                TextColumn::make('leader_name')
                    ->label('Ketua')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Jumlah anggota')
                    ->badge()
                    ->color('info'),
                TextColumn::make('member_names')
                    ->label('Anggota')
                    ->wrap(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum tergabung dalam kelompok')
            ->emptyStateDescription('Hubungi admin atau dosen jika kelompok Anda belum dibuat.');
    }
}
